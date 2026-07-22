1. Write a query to get Product name and quantity/unit

SELECT ProductName, QuantityPerUnit FROM products;

2. Write a query to get current Product list (Product ID and name).

// Discontinued = 0 are active products

SELECT id, ProductName FROM products WHERE Discontinued = 0;

3. Write a query to get most expense and least expensive Product list (name and
unit price).

(SELECT ProductName, UnitPrice
 FROM products
 ORDER BY UnitPrice DESC
 LIMIT 1)
UNION ALL
(SELECT ProductName, UnitPrice
 FROM products
 ORDER BY UnitPrice ASC
 LIMIT 1);

4. Write a query to get Product list (name, unit price) of above average price.

SELECT ProductName, UnitPrice
FROM products
WHERE UnitPrice > (SELECT AVG(UnitPrice) FROM products);

5. Write a query to get Product list (id, name, unit price) where current products cost
less than $20

SELECT id, ProductName, UnitPrice
FROM products
WHERE Discontinued = 0
  AND UnitPrice < 20;

6. Write a query to get Product list (name, units on order , units in stock) of stock is
less than the quantity on order.

SELECT ProductName, UnitsOnOrder, UnitStock
FROM products
WHERE UnitStock < UnitsOnOrder;
