/**
 * Adds one product to the cart.
 *
 * @param page
 * @param productId
 * @returns {Promise<void>}
 */

import kpURLS from './kpURLS';

/**
 *
 * Adds single product to cart.
 *
 * @param page
 * @param productId
 */
 const addSingleProductToCart = async (page, productId) => {
	const productSelector = productId;

	try {
		await page.goto(`${kpURLS.ADD_TO_CART}${productSelector}`);
		await page.goto(kpURLS.SHOP);
	} catch {
		// Proceed
	}
};

/**
 * Adds multiple products to the cart.
 *
 * @param page
 * @param products
 * @returns {Promise<void>}
 */
 const addMultipleProductsToCart = async (page, products) => {
	const timer = products.length;

	await page.waitForTimeout(timer * 800);

	(async function addEachProduct() {
		for (let i = 0; i < products.length + 1; i += 1) {
			await addSingleProductToCart(page, products[i]);
		}
	})();

	await page.waitForTimeout(timer * 800);
};

export default {
	addSingleProductToCart,
	addMultipleProductsToCart,
};