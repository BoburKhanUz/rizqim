// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data161.dart';

part 'get_v1_owner_pos_sync_signing_response.g.dart';

@JsonSerializable()
class GetV1OwnerPosSyncSigningResponse {
  const GetV1OwnerPosSyncSigningResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerPosSyncSigningResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerPosSyncSigningResponseFromJson(json);

  final bool success;
  final String message;
  final Data161 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerPosSyncSigningResponseToJson(this);
}
