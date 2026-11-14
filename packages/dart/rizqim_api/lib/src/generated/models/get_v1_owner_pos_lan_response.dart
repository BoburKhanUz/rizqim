// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data152.dart';

part 'get_v1_owner_pos_lan_response.g.dart';

@JsonSerializable()
class GetV1OwnerPosLanResponse {
  const GetV1OwnerPosLanResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerPosLanResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerPosLanResponseFromJson(json);

  final bool success;
  final String message;
  final Data152 data;

  Map<String, Object?> toJson() => _$GetV1OwnerPosLanResponseToJson(this);
}
