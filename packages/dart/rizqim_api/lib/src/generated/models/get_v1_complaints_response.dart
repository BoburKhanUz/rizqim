// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'complaint.dart';

part 'get_v1_complaints_response.g.dart';

@JsonSerializable()
class GetV1ComplaintsResponse {
  const GetV1ComplaintsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1ComplaintsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1ComplaintsResponseFromJson(json);

  final bool success;
  final String message;
  final List<Complaint> data;

  Map<String, Object?> toJson() => _$GetV1ComplaintsResponseToJson(this);
}
