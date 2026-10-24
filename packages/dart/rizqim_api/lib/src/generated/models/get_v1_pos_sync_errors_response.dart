// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data177.dart';

part 'get_v1_pos_sync_errors_response.g.dart';

@JsonSerializable()
class GetV1PosSyncErrorsResponse {
  const GetV1PosSyncErrorsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosSyncErrorsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1PosSyncErrorsResponseFromJson(json);

  final bool success;
  final String message;
  final Data177 data;

  Map<String, Object?> toJson() => _$GetV1PosSyncErrorsResponseToJson(this);
}
