-- DROP TABLE av_dtm_dom_meta.dtm_dom_index

CREATE TABLE av_dtm_dom_meta.dtm_dom_index
(
  ogc_fid serial NOT NULL,
  nummer varchar,
  the_geom geometry(POLYGON, 21781),
  CONSTRAINT maske_kanton_pkey PRIMARY KEY (ogc_fid),
  CONSTRAINT enforce_geotype_geometrie CHECK (geometrytype(the_geom) = 'POLYGON'::text OR the_geom IS NULL),
  CONSTRAINT enforce_srid_geometrie CHECK (st_srid(the_geom) = 21781)
)
WITH (
  OIDS=FALSE
);
ALTER TABLE av_dtm_dom_meta.dtm_dom_index OWNER TO stefan;
GRANT ALL ON TABLE av_dtm_dom_meta.dtm_dom_index TO stefan;
GRANT SELECT ON TABLE av_dtm_dom_meta.dtm_dom_index TO mspublic;
