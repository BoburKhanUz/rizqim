// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'complaint.dart';

part 'post_v1_complaints_id_escalate_response.g.dart';

@JsonSerializable()
class PostV1ComplaintsIdEscalateResponse {
  const PostV1ComplaintsIdEscalateResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1ComplaintsIdEscalateResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1ComplaintsIdEscalateResponseFromJson(json);

  final bool success;
  final String message;
  final Complaint? data;

  Map<String, Object?> toJson() =>
      _$PostV1ComplaintsIdEscalateResponseToJson(this);
}
