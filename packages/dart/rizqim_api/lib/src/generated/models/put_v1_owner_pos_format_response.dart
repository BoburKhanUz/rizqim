// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data111.dart';

part 'put_v1_owner_pos_format_response.g.dart';

@JsonSerializable()
class PutV1OwnerPosFormatResponse {
  const PutV1OwnerPosFormatResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerPosFormatResponse.fromJson(Map<String, Object?> json) =>
      _$PutV1OwnerPosFormatResponseFromJson(json);

  final bool success;
  final String message;
  final Data111 data;

  Map<String, Object?> toJson() => _$PutV1OwnerPosFormatResponseToJson(this);
}
