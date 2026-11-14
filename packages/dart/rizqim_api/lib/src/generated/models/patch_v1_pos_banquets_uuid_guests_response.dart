// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data231.dart';

part 'patch_v1_pos_banquets_uuid_guests_response.g.dart';

@JsonSerializable()
class PatchV1PosBanquetsUuidGuestsResponse {
  const PatchV1PosBanquetsUuidGuestsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PatchV1PosBanquetsUuidGuestsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PatchV1PosBanquetsUuidGuestsResponseFromJson(json);

  final bool success;
  final String message;
  final Data231 data;

  Map<String, Object?> toJson() =>
      _$PatchV1PosBanquetsUuidGuestsResponseToJson(this);
}
