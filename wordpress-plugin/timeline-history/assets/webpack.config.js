const path = require('path');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

const repoRoot = path.resolve(__dirname, '../../..');

module.exports = {
  ...defaultConfig,
  entry: {
    view: path.join(repoRoot, 'packages/timeline-core/src/view.ts'),
    builder: path.join(repoRoot, 'packages/timeline-builder/src/wp-standalone.tsx'),
  },
  output: {
    ...defaultConfig.output,
    path: path.resolve(__dirname, 'dist'),
    filename: '[name].js',
  },
  resolve: {
    ...defaultConfig.resolve,
    alias: {
      ...(defaultConfig.resolve && defaultConfig.resolve.alias),
      '@timeline-history/timeline-core': path.join(
        repoRoot,
        'packages/timeline-core/src/index.ts'
      ),
    },
    extensionAlias: {
      '.js': ['.ts', '.tsx', '.js', '.jsx'],
    },
  },
};