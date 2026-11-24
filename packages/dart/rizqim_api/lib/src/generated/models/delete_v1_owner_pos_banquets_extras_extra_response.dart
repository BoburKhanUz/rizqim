// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data137.dart';

part 'delete_v1_owner_pos_banquets_extras_extra_response.g.dart';

@JsonSerializable()
class DeleteV1OwnerPosBanquetsExtrasExtraResponse {
  const DeleteV1OwnerPosBanquetsExtrasExtraResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1OwnerPosBanquetsExtrasExtraResponse.fromJson(
    Map<String, Object?> json,
  ) => _$DeleteV1OwnerPosBanquetsExtrasExtraResponseFromJson(json);

  final bool success;
  final String message;
  final Data137 data;

  Map<String, Object?> toJson() =>
      _$DeleteV1OwnerPosBanquetsExtrasExtraResponseToJson(this);
}
