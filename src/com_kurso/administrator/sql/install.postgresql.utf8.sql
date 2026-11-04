--
-- KURSO for Joomla – database schema (PostgreSQL)
--

CREATE TABLE IF NOT EXISTS "#__kurso_queries" (
  "id" serial NOT NULL,
  "title" varchar(255) DEFAULT '' NOT NULL,
  "alias" varchar(191) DEFAULT '' NOT NULL,
  "note" varchar(255) DEFAULT '' NOT NULL,
  "graphql" text NOT NULL,
  "variables" text NOT NULL,
  "template" text NOT NULL,
  "refresh_interval" integer DEFAULT 60 NOT NULL,
  "state" smallint DEFAULT 1 NOT NULL,
  "created" timestamp without time zone NOT NULL,
  "created_by" integer DEFAULT 0 NOT NULL,
  "modified" timestamp without time zone NOT NULL,
  "modified_by" integer DEFAULT 0 NOT NULL,
  "checked_out" integer,
  "checked_out_time" timestamp without time zone,
  PRIMARY KEY ("id"),
  CONSTRAINT "#__kurso_queries_idx_alias" UNIQUE ("alias")
);

CREATE INDEX IF NOT EXISTS "#__kurso_queries_idx_state" ON "#__kurso_queries" ("state");
CREATE INDEX IF NOT EXISTS "#__kurso_queries_idx_checkout" ON "#__kurso_queries" ("checked_out");

CREATE TABLE IF NOT EXISTS "#__kurso_cache" (
  "query_id" integer NOT NULL,
  "data" text,
  "fetched_at" timestamp without time zone,
  "attempted_at" timestamp without time zone,
  "error" text,
  "error_at" timestamp without time zone,
  PRIMARY KEY ("query_id")
);

CREATE TABLE IF NOT EXISTS "#__kurso_settings" (
  "name" varchar(100) NOT NULL,
  "value" text NOT NULL,
  PRIMARY KEY ("name")
);
