const fs = require('fs');
const path = require('path');
const CopyPlugin = require('copy-webpack-plugin');

function filePatterns(type, extension) {
   const sourceDir = path.resolve(__dirname, 'src', type);
   if (!fs.existsSync(sourceDir)) {
      return [];
   }

   return fs
      .readdirSync(sourceDir)
      .filter((file) => file.endsWith(extension))
      .map((file) => ({
         from: path.join(sourceDir, file),
         to() {
            return path.resolve(__dirname, type, file);
         },
      }));
}

module.exports = {
   context: __dirname,
   entry: {},
   output: {
      path: path.resolve(__dirname, '.webpack-cache'),
      clean: false,
   },
   optimization: {
      minimize: false,
   },
   plugins: [
      new CopyPlugin({
         patterns: [
            ...filePatterns('css', '.css'),
            ...filePatterns('js', '.js'),
         ],
      }),
   ],
};
