// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data110.dart';

part 'get_v1_owner_pos_format_response.g.dart';

@JsonSerializable()
class GetV1OwnerPosFormatResponse {
  const GetV1OwnerPosFormatResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerPosFormatResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerPosFormatResponseFromJson(json);

  final bool success;
  final String message;
  final Data110 data;

  Map<String, Object?> toJson() => _$GetV1OwnerPosFormatResponseToJson(this);
}
