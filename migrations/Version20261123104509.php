<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261123104509 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, "action" VARCHAR(100) NOT NULL, entity_type VARCHAR(100) NOT NULL, entity_id INTEGER NOT NULL, old_value CLOB DEFAULT NULL, new_value CLOB DEFAULT NULL, ip_address VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL, user_id INTEGER DEFAULT NULL, CONSTRAINT FK_D62F2858A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_audit_action ON audit_logs ("action")');
        $this->addSql('CREATE INDEX idx_audit_entity ON audit_logs (entity_type, entity_id)');
        $this->addSql('CREATE INDEX IDX_D62F2858A76ED395 ON audit_logs (user_id)');
        $this->addSql('CREATE TABLE grades (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, numerical_value NUMERIC(5, 2) DEFAULT NULL, remarks VARCHAR(50) DEFAULT NULL, is_inc BOOLEAN DEFAULT 0 NOT NULL, updated_at DATETIME NOT NULL, student_id INTEGER NOT NULL, subject_id INTEGER NOT NULL, section_id INTEGER DEFAULT NULL, grading_period_id INTEGER NOT NULL, updated_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_3AE36110CB944F1A FOREIGN KEY (student_id) REFERENCES student_profiles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3AE3611023EDC87 FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3AE36110D823E37A FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3AE36110DF9D8D15 FOREIGN KEY (grading_period_id) REFERENCES grading_periods (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3AE36110896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3AE36110CB944F1A ON grades (student_id)');
        $this->addSql('CREATE INDEX IDX_3AE3611023EDC87 ON grades (subject_id)');
        $this->addSql('CREATE INDEX IDX_3AE36110D823E37A ON grades (section_id)');
        $this->addSql('CREATE INDEX IDX_3AE36110DF9D8D15 ON grades (grading_period_id)');
        $this->addSql('CREATE INDEX IDX_3AE36110896DBBDE ON grades (updated_by_id)');
        $this->addSql('CREATE TABLE grading_periods (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, code VARCHAR(20) NOT NULL, name VARCHAR(100) NOT NULL, start_date DATE DEFAULT NULL, end_date DATE DEFAULT NULL, sequence_order INTEGER NOT NULL, is_active BOOLEAN DEFAULT 1 NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5714BCFF77153098 ON grading_periods (code)');
        $this->addSql('CREATE TABLE incomplete_records (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, missing_category VARCHAR(50) NOT NULL, missing_item_title VARCHAR(200) NOT NULL, teacher_explanation CLOB NOT NULL, required_action CLOB NOT NULL, deadline DATETIME NOT NULL, status VARCHAR(20) NOT NULL, submission_note CLOB DEFAULT NULL, attachment_path VARCHAR(255) DEFAULT NULL, attachment_original_name VARCHAR(255) DEFAULT NULL, submitted_at DATETIME DEFAULT NULL, resolved_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, student_id INTEGER NOT NULL, subject_id INTEGER NOT NULL, section_id INTEGER DEFAULT NULL, grading_period_id INTEGER NOT NULL, teacher_id INTEGER DEFAULT NULL, created_by_id INTEGER DEFAULT NULL, updated_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_A584103DCB944F1A FOREIGN KEY (student_id) REFERENCES student_profiles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A584103D23EDC87 FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A584103DD823E37A FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A584103DDF9D8D15 FOREIGN KEY (grading_period_id) REFERENCES grading_periods (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A584103D41807E1D FOREIGN KEY (teacher_id) REFERENCES teacher_profiles (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A584103DB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A584103D896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_A584103DCB944F1A ON incomplete_records (student_id)');
        $this->addSql('CREATE INDEX IDX_A584103D23EDC87 ON incomplete_records (subject_id)');
        $this->addSql('CREATE INDEX IDX_A584103DD823E37A ON incomplete_records (section_id)');
        $this->addSql('CREATE INDEX IDX_A584103DDF9D8D15 ON incomplete_records (grading_period_id)');
        $this->addSql('CREATE INDEX IDX_A584103D41807E1D ON incomplete_records (teacher_id)');
        $this->addSql('CREATE INDEX IDX_A584103DB03A8386 ON incomplete_records (created_by_id)');
        $this->addSql('CREATE INDEX IDX_A584103D896DBBDE ON incomplete_records (updated_by_id)');
        $this->addSql('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(150) NOT NULL, message CLOB NOT NULL, link VARCHAR(255) DEFAULT NULL, type VARCHAR(50) NOT NULL, is_read BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_notification_read ON notifications (is_read)');
        $this->addSql('CREATE INDEX IDX_6000B0D3A76ED395 ON notifications (user_id)');
        $this->addSql('CREATE TABLE section_subjects (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, section_id INTEGER NOT NULL, subject_id INTEGER NOT NULL, teacher_id INTEGER NOT NULL, CONSTRAINT FK_3B48C34BD823E37A FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3B48C34B23EDC87 FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3B48C34B41807E1D FOREIGN KEY (teacher_id) REFERENCES teacher_profiles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3B48C34BD823E37A ON section_subjects (section_id)');
        $this->addSql('CREATE INDEX IDX_3B48C34B23EDC87 ON section_subjects (subject_id)');
        $this->addSql('CREATE INDEX IDX_3B48C34B41807E1D ON section_subjects (teacher_id)');
        $this->addSql('CREATE TABLE sections (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, academic_year VARCHAR(50) NOT NULL, semester VARCHAR(50) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2B9643985E237E06 ON sections (name)');
        $this->addSql('CREATE TABLE student_profiles (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, student_number VARCHAR(50) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, year_level VARCHAR(50) NOT NULL, user_id INTEGER NOT NULL, section_id INTEGER DEFAULT NULL, CONSTRAINT FK_38BBC9ABA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_38BBC9ABD823E37A FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_38BBC9AB18A6C7D4 ON student_profiles (student_number)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_38BBC9ABA76ED395 ON student_profiles (user_id)');
        $this->addSql('CREATE INDEX IDX_38BBC9ABD823E37A ON student_profiles (section_id)');
        $this->addSql('CREATE TABLE subjects (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, code VARCHAR(20) NOT NULL, title VARCHAR(150) NOT NULL, description CLOB DEFAULT NULL, units INTEGER NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AB25991777153098 ON subjects (code)');
        $this->addSql('CREATE TABLE teacher_profiles (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, employee_number VARCHAR(50) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, department VARCHAR(100) DEFAULT NULL, title VARCHAR(50) DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_8A2636BBA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8A2636BBBFA1DBC1 ON teacher_profiles (employee_number)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8A2636BBA76ED395 ON teacher_profiles (user_id)');
        $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, is_active BOOLEAN DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9F85E0677 ON users (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('DROP TABLE grades');
        $this->addSql('DROP TABLE grading_periods');
        $this->addSql('DROP TABLE incomplete_records');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE section_subjects');
        $this->addSql('DROP TABLE sections');
        $this->addSql('DROP TABLE student_profiles');
        $this->addSql('DROP TABLE subjects');
        $this->addSql('DROP TABLE teacher_profiles');
        $this->addSql('DROP TABLE users');
    }
}
