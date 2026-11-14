// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data135.dart';

part 'patch_v1_owner_pos_banquets_extras_extra_response.g.dart';

@JsonSerializable()
class PatchV1OwnerPosBanquetsExtrasExtraResponse {
  const PatchV1OwnerPosBanquetsExtrasExtraResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PatchV1OwnerPosBanquetsExtrasExtraResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PatchV1OwnerPosBanquetsExtrasExtraResponseFromJson(json);

  final bool success;
  final String message;
  final Data135 data;

  Map<String, Object?> toJson() =>
      _$PatchV1OwnerPosBanquetsExtrasExtraResponseToJson(this);
}
