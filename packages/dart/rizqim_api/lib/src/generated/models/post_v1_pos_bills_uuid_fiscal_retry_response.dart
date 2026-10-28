// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data119.dart';

part 'post_v1_pos_bills_uuid_fiscal_retry_response.g.dart';

@JsonSerializable()
class PostV1PosBillsUuidFiscalRetryResponse {
  const PostV1PosBillsUuidFiscalRetryResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosBillsUuidFiscalRetryResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosBillsUuidFiscalRetryResponseFromJson(json);

  final bool success;
  final String message;
  final Data119 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosBillsUuidFiscalRetryResponseToJson(this);
}
