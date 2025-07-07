-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 08, 2025 at 01:10 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `s22102131_foundit`
--

-- --------------------------------------------------------

--
-- Table structure for table `administrator`
--

CREATE TABLE `administrator` (
  `PersonID` int(11) NOT NULL,
  `office_location` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `approval_status`
--

CREATE TABLE `approval_status` (
  `ApprovalStatusID` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `approval_status`
--

INSERT INTO `approval_status` (`ApprovalStatusID`, `status_name`, `description`) VALUES
(1, 'Pending', 'Report is awaiting review'),
(2, 'Approved', 'Report has been approved and is visible'),
(3, 'Rejected', 'Report has been rejected'),
(4, 'Under Review', 'Report is currently being reviewed'),
(5, 'Archived', 'Report has been archived');

-- --------------------------------------------------------

--
-- Table structure for table `claim`
--

CREATE TABLE `claim` (
  `ClaimID` int(11) NOT NULL,
  `ContactID` int(11) NOT NULL,
  `UserID_claimant` int(11) NOT NULL,
  `AdminID_processor` int(11) NOT NULL,
  `claim_date` timestamp NULL DEFAULT current_timestamp(),
  `claim_status` enum('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
  `interrogation_notes` text DEFAULT NULL,
  `passed_interrogation` tinyint(1) DEFAULT NULL,
  `resolution_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_request`
--

CREATE TABLE `contact_request` (
  `ContactID` int(11) NOT NULL,
  `UserID_claimant` int(11) NOT NULL,
  `PostID` int(11) NOT NULL,
  `AdminID_reviewer` int(11) DEFAULT NULL,
  `ownership_description` text NOT NULL,
  `submission_date` timestamp NULL DEFAULT current_timestamp(),
  `detailed_description` text DEFAULT NULL,
  `evidence_details` text DEFAULT NULL,
  `review_status` enum('pending','approved','rejected','under_review') NOT NULL DEFAULT 'pending',
  `review_date` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feed_post`
--

CREATE TABLE `feed_post` (
  `PostID` int(11) NOT NULL,
  `ReportID` int(11) NOT NULL,
  `post_date` timestamp NULL DEFAULT current_timestamp(),
  `post_status` enum('active','archived','deleted') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `found`
--

CREATE TABLE `found` (
  `ReportID` int(11) NOT NULL,
  `location_found` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lost`
--

CREATE TABLE `lost` (
  `ReportID` int(11) NOT NULL,
  `location_last_seen` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lost`
--

INSERT INTO `lost` (`ReportID`, `location_last_seen`) VALUES
(1, 'Bunzel Building, ROOM LB 468TC');

-- --------------------------------------------------------

--
-- Table structure for table `lostitems`
--

CREATE TABLE `lostitems` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(200) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected','archived') NOT NULL DEFAULT 'pending',
  `date_reported` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `person`
--

CREATE TABLE `person` (
  `PersonID` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `person`
--

INSERT INTO `person` (`PersonID`, `name`, `email`, `phone_number`, `password`, `created_at`, `updated_at`) VALUES
(1, 'Test Test', 'test@gmail.com', '09653422342', '$2y$10$nkuTDJ3iAL4ZmBtFYnh7OuMGPtVvdxZWI6h9fz3OAV6qp05uICjsW', '2025-06-23 13:26:06', '2025-06-23 13:26:06');

-- --------------------------------------------------------

--
-- Table structure for table `report`
--

CREATE TABLE `report` (
  `ReportID` int(11) NOT NULL,
  `UserID_submitter` int(11) NOT NULL,
  `AdminID_reviewer` int(11) DEFAULT NULL,
  `report_type` enum('found','lost') NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `incident_date` date NOT NULL,
  `submission_date` timestamp NULL DEFAULT current_timestamp(),
  `claimedYN` tinyint(1) DEFAULT 0,
  `archivedYN` tinyint(1) DEFAULT 0,
  `archivedDate` timestamp NULL DEFAULT NULL,
  `reviewDate` timestamp NULL DEFAULT NULL,
  `reviewNote` text DEFAULT NULL,
  `ApprovalStatusID` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `report`
--

INSERT INTO `report` (`ReportID`, `UserID_submitter`, `AdminID_reviewer`, `report_type`, `item_name`, `description`, `image_path`, `incident_date`, `submission_date`, `claimedYN`, `archivedYN`, `archivedDate`, `reviewDate`, `reviewNote`, `ApprovalStatusID`) VALUES
(1, 1, NULL, 'lost', 'iPhone 18 Pro Max', 'Do I lost my 18 pro max if u see it pls call 09322102131, thx homie!', 'uploads/item_686c53a2866135.47012837.jpg', '2025-07-08', '2025-07-07 23:09:22', 0, 0, NULL, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `report_images`
--

CREATE TABLE `report_images` (
  `ImageID` int(11) NOT NULL,
  `ReportID` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `image_name` varchar(255) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `PersonID` int(11) NOT NULL,
  `role` enum('student','staff','faculty') NOT NULL DEFAULT 'student',
  `student_id` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`PersonID`, `role`, `student_id`, `department`) VALUES
(1, 'student', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `administrator`
--
ALTER TABLE `administrator`
  ADD PRIMARY KEY (`PersonID`);

--
-- Indexes for table `approval_status`
--
ALTER TABLE `approval_status`
  ADD PRIMARY KEY (`ApprovalStatusID`);

--
-- Indexes for table `claim`
--
ALTER TABLE `claim`
  ADD PRIMARY KEY (`ClaimID`),
  ADD KEY `ContactID` (`ContactID`),
  ADD KEY `UserID_claimant` (`UserID_claimant`),
  ADD KEY `AdminID_processor` (`AdminID_processor`);

--
-- Indexes for table `contact_request`
--
ALTER TABLE `contact_request`
  ADD PRIMARY KEY (`ContactID`),
  ADD KEY `UserID_claimant` (`UserID_claimant`),
  ADD KEY `PostID` (`PostID`),
  ADD KEY `AdminID_reviewer` (`AdminID_reviewer`);

--
-- Indexes for table `feed_post`
--
ALTER TABLE `feed_post`
  ADD PRIMARY KEY (`PostID`),
  ADD KEY `ReportID` (`ReportID`);

--
-- Indexes for table `found`
--
ALTER TABLE `found`
  ADD PRIMARY KEY (`ReportID`);

--
-- Indexes for table `lost`
--
ALTER TABLE `lost`
  ADD PRIMARY KEY (`ReportID`);

--
-- Indexes for table `lostitems`
--
ALTER TABLE `lostitems`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `person`
--
ALTER TABLE `person`
  ADD PRIMARY KEY (`PersonID`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `report`
--
ALTER TABLE `report`
  ADD PRIMARY KEY (`ReportID`),
  ADD KEY `UserID_submitter` (`UserID_submitter`),
  ADD KEY `AdminID_reviewer` (`AdminID_reviewer`),
  ADD KEY `ApprovalStatusID` (`ApprovalStatusID`);

--
-- Indexes for table `report_images`
--
ALTER TABLE `report_images`
  ADD PRIMARY KEY (`ImageID`),
  ADD KEY `ReportID` (`ReportID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`PersonID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `approval_status`
--
ALTER TABLE `approval_status`
  MODIFY `ApprovalStatusID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `claim`
--
ALTER TABLE `claim`
  MODIFY `ClaimID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_request`
--
ALTER TABLE `contact_request`
  MODIFY `ContactID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feed_post`
--
ALTER TABLE `feed_post`
  MODIFY `PostID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lostitems`
--
ALTER TABLE `lostitems`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `person`
--
ALTER TABLE `person`
  MODIFY `PersonID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `report`
--
ALTER TABLE `report`
  MODIFY `ReportID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `report_images`
--
ALTER TABLE `report_images`
  MODIFY `ImageID` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `administrator`
--
ALTER TABLE `administrator`
  ADD CONSTRAINT `Administrator_ibfk_1` FOREIGN KEY (`PersonID`) REFERENCES `person` (`PersonID`) ON DELETE CASCADE;

--
-- Constraints for table `claim`
--
ALTER TABLE `claim`
  ADD CONSTRAINT `claim_ibfk_1` FOREIGN KEY (`ContactID`) REFERENCES `contact_request` (`ContactID`) ON DELETE CASCADE,
  ADD CONSTRAINT `claim_ibfk_2` FOREIGN KEY (`UserID_claimant`) REFERENCES `users` (`PersonID`) ON DELETE CASCADE,
  ADD CONSTRAINT `claim_ibfk_3` FOREIGN KEY (`AdminID_processor`) REFERENCES `administrator` (`PersonID`) ON DELETE CASCADE;

--
-- Constraints for table `contact_request`
--
ALTER TABLE `contact_request`
  ADD CONSTRAINT `contact_request_ibfk_1` FOREIGN KEY (`UserID_claimant`) REFERENCES `users` (`PersonID`) ON DELETE CASCADE,
  ADD CONSTRAINT `contact_request_ibfk_2` FOREIGN KEY (`PostID`) REFERENCES `feed_post` (`PostID`) ON DELETE CASCADE,
  ADD CONSTRAINT `contact_request_ibfk_3` FOREIGN KEY (`AdminID_reviewer`) REFERENCES `administrator` (`PersonID`) ON DELETE SET NULL;

--
-- Constraints for table `feed_post`
--
ALTER TABLE `feed_post`
  ADD CONSTRAINT `feed_post_ibfk_1` FOREIGN KEY (`ReportID`) REFERENCES `report` (`ReportID`) ON DELETE CASCADE;

--
-- Constraints for table `found`
--
ALTER TABLE `found`
  ADD CONSTRAINT `found_ibfk_1` FOREIGN KEY (`ReportID`) REFERENCES `report` (`ReportID`) ON DELETE CASCADE;

--
-- Constraints for table `lost`
--
ALTER TABLE `lost`
  ADD CONSTRAINT `lost_ibfk_1` FOREIGN KEY (`ReportID`) REFERENCES `report` (`ReportID`) ON DELETE CASCADE;

--
-- Constraints for table `lostitems`
--
ALTER TABLE `lostitems`
  ADD CONSTRAINT `LostItems_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`PersonID`) ON DELETE CASCADE;

--
-- Constraints for table `report`
--
ALTER TABLE `report`
  ADD CONSTRAINT `report_ibfk_1` FOREIGN KEY (`UserID_submitter`) REFERENCES `users` (`PersonID`) ON DELETE CASCADE,
  ADD CONSTRAINT `report_ibfk_2` FOREIGN KEY (`AdminID_reviewer`) REFERENCES `administrator` (`PersonID`) ON DELETE SET NULL,
  ADD CONSTRAINT `report_ibfk_3` FOREIGN KEY (`ApprovalStatusID`) REFERENCES `approval_status` (`ApprovalStatusID`) ON DELETE SET NULL;

--
-- Constraints for table `report_images`
--
ALTER TABLE `report_images`
  ADD CONSTRAINT `report_images_ibfk_1` FOREIGN KEY (`ReportID`) REFERENCES `report` (`ReportID`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `Users_ibfk_1` FOREIGN KEY (`PersonID`) REFERENCES `person` (`PersonID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
