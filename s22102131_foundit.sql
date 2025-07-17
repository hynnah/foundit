-- phpMyAdmin SQL Dump
-- version 5.2.1deb1+deb12u1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 17, 2025 at 11:27 AM
-- Server version: 10.11.11-MariaDB-0+deb12u1
-- PHP Version: 8.2.28

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
-- Table structure for table `Administrator`
--

CREATE TABLE `Administrator` (
  `AdminID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Administrator`
--

INSERT INTO `Administrator` (`AdminID`) VALUES
(1);

-- --------------------------------------------------------

--
-- Table structure for table `ApprovalStatus`
--

CREATE TABLE `ApprovalStatus` (
  `ApprovalStatusID` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ApprovalStatus`
--

INSERT INTO `ApprovalStatus` (`ApprovalStatusID`, `status_name`, `description`) VALUES
(1, 'Pending', 'Awaiting admin review'),
(2, 'Approved', 'Approved by admin for public posting'),
(3, 'Rejected', 'Rejected by admin'),
(4, 'Under Review', 'Currently being reviewed by admin'),
(5, 'Needs Revision', 'Requires changes before approval'),
(6, 'Escalated', 'Escalated for higher level review');

-- --------------------------------------------------------

--
-- Table structure for table `Claim`
--

CREATE TABLE `Claim` (
  `ClaimID` int(11) NOT NULL,
  `ContactID` int(11) NOT NULL,
  `UserID_claimant` int(11) NOT NULL,
  `AdminID_processor` int(11) NOT NULL,
  `claim_date` datetime NOT NULL DEFAULT current_timestamp(),
  `claim_status` enum('Processing','Under Investigation','Awaiting Verification','Completed','Rejected','Cancelled') DEFAULT 'Processing',
  `interrogation_notes` text DEFAULT NULL,
  `passed_interrogationYN` tinyint(1) DEFAULT NULL,
  `resolution_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ContactRequest`
--

CREATE TABLE `ContactRequest` (
  `ContactID` int(11) NOT NULL,
  `UserID_claimant` int(11) NOT NULL,
  `PostID` int(11) NOT NULL,
  `AdminID_reviewer` int(11) DEFAULT NULL,
  `ownership_description` text NOT NULL,
  `item_appearance` text DEFAULT NULL,
  `location_lost` varchar(255) DEFAULT NULL,
  `date_lost` date DEFAULT NULL,
  `evidence_file_path` varchar(255) DEFAULT NULL,
  `evidence_file_name` varchar(255) DEFAULT NULL,
  `unique_marks` text DEFAULT NULL,
  `submission_date` datetime NOT NULL DEFAULT current_timestamp(),
  `review_status` enum('Pending','Under Review','Approved','Rejected','Requires More Info') DEFAULT 'Pending',
  `review_date` datetime DEFAULT NULL,
  `review_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `FeedPost`
--

CREATE TABLE `FeedPost` (
  `PostID` int(11) NOT NULL,
  `ReportID` int(11) NOT NULL,
  `post_date` datetime NOT NULL DEFAULT current_timestamp(),
  `post_status` enum('Active','Archived','Claimed','Expired','Hidden') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Found`
--

CREATE TABLE `Found` (
  `ReportID` int(11) NOT NULL,
  `location_found` varchar(255) NOT NULL,
  `vague_item_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Lost`
--

CREATE TABLE `Lost` (
  `ReportID` int(11) NOT NULL,
  `location_last_seen` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Person`
--

CREATE TABLE `Person` (
  `PersonID` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `person_type` enum('User','Administrator') NOT NULL,
  `account_status` enum('Active','Deactivated','Suspended') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Person`
--

INSERT INTO `Person` (`PersonID`, `name`, `email`, `phone_number`, `password`, `person_type`, `account_status`) VALUES
(1, 'System Admin', 'admin@usc.edu.ph', '09123456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'Active'),
(2, 'John Student', 'john.student@usc.edu.ph', '09987654321', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'User', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `Report`
--

CREATE TABLE `Report` (
  `ReportID` int(11) NOT NULL,
  `UserID_submitter` int(11) NOT NULL,
  `AdminID_reviewer` int(11) DEFAULT NULL,
  `report_type` enum('Lost','Found') NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `incident_date` datetime NOT NULL,
  `submission_date` datetime NOT NULL DEFAULT current_timestamp(),
  `claimedYN` tinyint(1) DEFAULT 0,
  `archiveYN` tinyint(1) DEFAULT 0,
  `archiveDate` datetime DEFAULT NULL,
  `reviewDate` datetime DEFAULT NULL,
  `reviewNote` text DEFAULT NULL,
  `ApprovalStatusID` int(11) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `User`
--

CREATE TABLE `User` (
  `UserID` int(11) NOT NULL,
  `role` enum('Student','Teacher','Staff','Visitor','Cashier','Guard','Janitor','Alumni','Contractor') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `User`
--

INSERT INTO `User` (`UserID`, `role`) VALUES
(1, 'Student'),
(2, 'Student');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `Administrator`
--
ALTER TABLE `Administrator`
  ADD PRIMARY KEY (`AdminID`);

--
-- Indexes for table `ApprovalStatus`
--
ALTER TABLE `ApprovalStatus`
  ADD PRIMARY KEY (`ApprovalStatusID`);

--
-- Indexes for table `Claim`
--
ALTER TABLE `Claim`
  ADD PRIMARY KEY (`ClaimID`),
  ADD KEY `ContactID` (`ContactID`),
  ADD KEY `UserID_claimant` (`UserID_claimant`),
  ADD KEY `AdminID_processor` (`AdminID_processor`);

--
-- Indexes for table `ContactRequest`
--
ALTER TABLE `ContactRequest`
  ADD PRIMARY KEY (`ContactID`),
  ADD KEY `UserID_claimant` (`UserID_claimant`),
  ADD KEY `PostID` (`PostID`),
  ADD KEY `AdminID_reviewer` (`AdminID_reviewer`);

--
-- Indexes for table `FeedPost`
--
ALTER TABLE `FeedPost`
  ADD PRIMARY KEY (`PostID`),
  ADD KEY `ReportID` (`ReportID`);

--
-- Indexes for table `Found`
--
ALTER TABLE `Found`
  ADD PRIMARY KEY (`ReportID`);

--
-- Indexes for table `Lost`
--
ALTER TABLE `Lost`
  ADD PRIMARY KEY (`ReportID`);

--
-- Indexes for table `Person`
--
ALTER TABLE `Person`
  ADD PRIMARY KEY (`PersonID`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `Report`
--
ALTER TABLE `Report`
  ADD PRIMARY KEY (`ReportID`),
  ADD KEY `UserID_submitter` (`UserID_submitter`),
  ADD KEY `AdminID_reviewer` (`AdminID_reviewer`),
  ADD KEY `ApprovalStatusID` (`ApprovalStatusID`);

--
-- Indexes for table `User`
--
ALTER TABLE `User`
  ADD PRIMARY KEY (`UserID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ApprovalStatus`
--
ALTER TABLE `ApprovalStatus`
  MODIFY `ApprovalStatusID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `Claim`
--
ALTER TABLE `Claim`
  MODIFY `ClaimID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ContactRequest`
--
ALTER TABLE `ContactRequest`
  MODIFY `ContactID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `FeedPost`
--
ALTER TABLE `FeedPost`
  MODIFY `PostID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Person`
--
ALTER TABLE `Person`
  MODIFY `PersonID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `Report`
--
ALTER TABLE `Report`
  MODIFY `ReportID` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `Administrator`
--
ALTER TABLE `Administrator`
  ADD CONSTRAINT `Administrator_ibfk_1` FOREIGN KEY (`AdminID`) REFERENCES `Person` (`PersonID`) ON DELETE CASCADE;

--
-- Constraints for table `Claim`
--
ALTER TABLE `Claim`
  ADD CONSTRAINT `Claim_ibfk_1` FOREIGN KEY (`ContactID`) REFERENCES `ContactRequest` (`ContactID`),
  ADD CONSTRAINT `Claim_ibfk_2` FOREIGN KEY (`UserID_claimant`) REFERENCES `User` (`UserID`),
  ADD CONSTRAINT `Claim_ibfk_3` FOREIGN KEY (`AdminID_processor`) REFERENCES `Administrator` (`AdminID`);

--
-- Constraints for table `ContactRequest`
--
ALTER TABLE `ContactRequest`
  ADD CONSTRAINT `ContactRequest_ibfk_1` FOREIGN KEY (`UserID_claimant`) REFERENCES `User` (`UserID`),
  ADD CONSTRAINT `ContactRequest_ibfk_2` FOREIGN KEY (`PostID`) REFERENCES `FeedPost` (`PostID`),
  ADD CONSTRAINT `ContactRequest_ibfk_3` FOREIGN KEY (`AdminID_reviewer`) REFERENCES `Administrator` (`AdminID`);

--
-- Constraints for table `FeedPost`
--
ALTER TABLE `FeedPost`
  ADD CONSTRAINT `FeedPost_ibfk_1` FOREIGN KEY (`ReportID`) REFERENCES `Report` (`ReportID`);

--
-- Constraints for table `Found`
--
ALTER TABLE `Found`
  ADD CONSTRAINT `Found_ibfk_1` FOREIGN KEY (`ReportID`) REFERENCES `Report` (`ReportID`) ON DELETE CASCADE;

--
-- Constraints for table `Lost`
--
ALTER TABLE `Lost`
  ADD CONSTRAINT `Lost_ibfk_1` FOREIGN KEY (`ReportID`) REFERENCES `Report` (`ReportID`) ON DELETE CASCADE;

--
-- Constraints for table `Report`
--
ALTER TABLE `Report`
  ADD CONSTRAINT `Report_ibfk_1` FOREIGN KEY (`UserID_submitter`) REFERENCES `User` (`UserID`),
  ADD CONSTRAINT `Report_ibfk_2` FOREIGN KEY (`AdminID_reviewer`) REFERENCES `Administrator` (`AdminID`),
  ADD CONSTRAINT `Report_ibfk_3` FOREIGN KEY (`ApprovalStatusID`) REFERENCES `ApprovalStatus` (`ApprovalStatusID`);

--
-- Constraints for table `User`
--
ALTER TABLE `User`
  ADD CONSTRAINT `User_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `Person` (`PersonID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
