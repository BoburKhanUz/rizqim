// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'complaint.dart';

part 'post_v1_complaints_response.g.dart';

@JsonSerializable()
class PostV1ComplaintsResponse {
  const PostV1ComplaintsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1ComplaintsResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1ComplaintsResponseFromJson(json);

  final bool success;
  final String message;
  final Complaint data;

  Map<String, Object?> toJson() => _$PostV1ComplaintsResponseToJson(this);
}
