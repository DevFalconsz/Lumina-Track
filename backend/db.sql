CREATE TABLE entregas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nfe_key VARCHAR(44) NOT NULL UNIQUE,
    dest_name VARCHAR(255) NOT NULL,
    dest_cep VARCHAR(9) NOT NULL,
    dest_logradouro VARCHAR(255),
    dest_numero VARCHAR(10),
    dest_bairro VARCHAR(100),
    dest_municipio VARCHAR(100),
    dest_uf VARCHAR(2),
    dest_lat DECIMAL(10, 8),
    dest_lng DECIMAL(11, 8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entrega_id INT NOT NULL,
    status VARCHAR(255) NOT NULL,
    event_date TIMESTAMP NOT NULL,
    FOREIGN KEY (entrega_id) REFERENCES entregas(id) ON DELETE CASCADE
);
