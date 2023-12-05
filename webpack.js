const path = require("path");
const webpackConfig = require("@nextcloud/webpack-vue-config");
const WindiCSSWebpackPlugin = require("windicss-webpack-plugin");

// webpackConfig.mode = "production"; // 开启生产模式

// 入口文件设置
webpackConfig.entry.admin = path.join(__dirname, "src", "admin");
webpackConfig.entry.dashboard = path.join(__dirname, "src", "dashboard");

// 插件
webpackConfig.plugins.push(new WindiCSSWebpackPlugin());

// 性能提示
webpackConfig.performance = {
	hints: "warning",
	maxEntrypointSize: 512000,
	maxAssetSize: 512000,
};

module.exports = webpackConfig;
