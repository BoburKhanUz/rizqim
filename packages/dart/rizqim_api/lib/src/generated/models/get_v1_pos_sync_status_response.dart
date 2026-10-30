// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data267.dart';

part 'get_v1_pos_sync_status_response.g.dart';

@JsonSerializable()
class GetV1PosSyncStatusResponse {
  const GetV1PosSyncStatusResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosSyncStatusResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1PosSyncStatusResponseFromJson(json);

  final bool success;
  final String message;
  final Data267 data;

  Map<String, Object?> toJson() => _$GetV1PosSyncStatusResponseToJson(this);
}
