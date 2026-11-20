// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data160.dart';

part 'put_v1_owner_pos_sync_signing_response.g.dart';

@JsonSerializable()
class PutV1OwnerPosSyncSigningResponse {
  const PutV1OwnerPosSyncSigningResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerPosSyncSigningResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerPosSyncSigningResponseFromJson(json);

  final bool success;
  final String message;
  final Data160 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerPosSyncSigningResponseToJson(this);
}
