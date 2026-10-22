-- The current marketplace uses buyer rooms, farmer offers, and room orders.
-- These legacy auction tables were empty and are not referenced by application code.
DROP TABLE IF EXISTS bids;
DROP TABLE IF EXISTS auction_history;
DROP TABLE IF EXISTS auctions;
