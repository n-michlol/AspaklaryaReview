CREATE TABLE /*_*/aspaklarya_review_queue (
    arq_id INT PRIMARY KEY AUTO_INCREMENT,
    arq_filename VARCHAR(255) NOT NULL,
    arq_page_id INT NOT NULL,
    arq_requester INT NOT NULL,
    arq_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    arq_status ENUM('pending', 'approved', 'removed', 'edited') DEFAULT 'pending',
    arq_reviewer INT DEFAULT NULL,
    arq_review_timestamp TIMESTAMP NULL
) /*$wgDBTableOptions*/;

CREATE INDEX arq_status_timestamp ON /*_*/aspaklarya_review_queue (arq_status, arq_timestamp);
CREATE INDEX arq_filename ON /*_*/aspaklarya_review_queue (arq_filename);