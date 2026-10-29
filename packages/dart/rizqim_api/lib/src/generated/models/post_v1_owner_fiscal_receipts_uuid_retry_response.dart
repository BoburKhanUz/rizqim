// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data80.dart';

part 'post_v1_owner_fiscal_receipts_uuid_retry_response.g.dart';

@JsonSerializable()
class PostV1OwnerFiscalReceiptsUuidRetryResponse {
  const PostV1OwnerFiscalReceiptsUuidRetryResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerFiscalReceiptsUuidRetryResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerFiscalReceiptsUuidRetryResponseFromJson(json);

  final bool success;
  final String message;
  final Data80 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerFiscalReceiptsUuidRetryResponseToJson(this);
}
