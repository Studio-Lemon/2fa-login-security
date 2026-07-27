#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const projectRoot = path.resolve(__dirname, '..');
const defaultFiles = ['src/css/admin.css', 'src/css/admin-global.css'];
const searchRoots = ['views', 'classes', 'src/js'];

const args = process.argv.slice(2);
const writeChanges = args.includes('--write');
const targetFiles = args.filter((argument) => !argument.startsWith('--'));

const filesToProcess = targetFiles.length > 0 ? targetFiles : defaultFiles;
const referenceCache = new Map();

function splitSelectors(selectorText) {
   const selectors = [];
   let current = '';
   let depth = 0;
   let quote = null;
   let escaped = false;

   for (const character of selectorText) {
      if (quote) {
         current += character;

         if (escaped) {
            escaped = false;
            continue;
         }

         if (character === '\\') {
            escaped = true;
            continue;
         }

         if (character === quote) {
            quote = null;
         }

         continue;
      }

      if (character === '"' || character === "'") {
         quote = character;
         current += character;
         continue;
      }

      if (character === '(' || character === '[') {
         depth += 1;
         current += character;
         continue;
      }

      if (character === ')' || character === ']') {
         depth = Math.max(0, depth - 1);
         current += character;
         continue;
      }

      if (character === ',' && depth === 0) {
         if (current.trim()) {
            selectors.push(current.trim());
         }

         current = '';
         continue;
      }

      current += character;
   }

   if (current.trim()) {
      selectors.push(current.trim());
   }

   return selectors;
}

function findMatchingBrace(text, openingBraceIndex) {
   let depth = 0;
   let quote = null;
   let escaped = false;

   for (let index = openingBraceIndex; index < text.length; index += 1) {
      const character = text[index];

      if (quote) {
         if (escaped) {
            escaped = false;
            continue;
         }

         if (character === '\\') {
            escaped = true;
            continue;
         }

         if (character === quote) {
            quote = null;
         }

         continue;
      }

      if (character === '"' || character === "'") {
         quote = character;
         continue;
      }

      if (character === '{') {
         depth += 1;
      } else if (character === '}') {
         depth -= 1;

         if (depth === 0) {
            return index;
         }
      }
   }

   throw new Error(`Unmatched brace starting at index ${openingBraceIndex}`);
}

function normalizeSelector(selector) {
   return selector.replace(/::?[a-zA-Z-]+(?:\([^()]*\))?/g, '');
}

function selectorTokens(selector) {
   const normalizedSelector = normalizeSelector(selector);
   const tokens = [];
   const tokenMatches = normalizedSelector.match(/[.#][A-Za-z_][A-Za-z0-9_-]*/g) || [];

   for (const token of tokenMatches) {
      tokens.push(token.slice(1));
   }

   return tokens;
}

function isReferenced(token) {
   if (referenceCache.has(token)) {
      return referenceCache.get(token);
   }

   try {
      execFileSync('rg', ['-n', '-F', token, ...searchRoots], {
         cwd: projectRoot,
         stdio: ['ignore', 'ignore', 'ignore'],
      });

      referenceCache.set(token, true);
      return true;
   } catch (error) {
      referenceCache.set(token, false);
      return false;
   }
}

function shouldKeepRule(selectorHeader) {
   const selectors = splitSelectors(selectorHeader);

   if (selectors.length === 0) {
      return true;
   }

   for (const selector of selectors) {
      const tokens = selectorTokens(selector);

      if (tokens.length === 0) {
         return true;
      }

      if (tokens.some((token) => isReferenced(token))) {
         return true;
      }
   }

   return false;
}

function processCss(text) {
   let output = '';
   let buffer = '';
   let index = 0;

   while (index < text.length) {
      const character = text[index];
      buffer += character;

      if (character === '{') {
         const header = buffer.slice(0, -1);
         const closeIndex = findMatchingBrace(text, index);
         const innerContent = text.slice(index + 1, closeIndex);
         const trimmedHeader = header.trim();

         if (trimmedHeader.startsWith('@')) {
            const processedInner = processCss(innerContent);

            if (processedInner.trim() || trimmedHeader.startsWith('@charset')) {
               output += `${header}{${processedInner}}`;
            }
         } else if (shouldKeepRule(trimmedHeader)) {
            output += `${header}{${innerContent}}`;
         }

         buffer = '';
         index = closeIndex + 1;
         continue;
      }

      if (character === ';' && buffer.trimStart().startsWith('@') && !buffer.includes('{')) {
         output += buffer;
         buffer = '';
      }

      index += 1;
   }

   output += buffer;
   return output;
}

function pruneFile(relativePath) {
   const filePath = path.join(projectRoot, relativePath);
   const original = fs.readFileSync(filePath, 'utf8');
   const cleaned = processCss(original);

   if (cleaned === original) {
      console.log(`${relativePath}: no changes`);
      return;
   }

   const removedBytes = original.length - cleaned.length;
   console.log(`${relativePath}: ${writeChanges ? 'updated' : 'would update'} (${removedBytes} bytes removed)`);

   if (writeChanges) {
      fs.writeFileSync(filePath, cleaned);
   }
}

for (const relativePath of filesToProcess) {
   pruneFile(relativePath);
}