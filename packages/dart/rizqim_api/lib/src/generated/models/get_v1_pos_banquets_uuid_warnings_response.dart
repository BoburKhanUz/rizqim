// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data244.dart';

part 'get_v1_pos_banquets_uuid_warnings_response.g.dart';

@JsonSerializable()
class GetV1PosBanquetsUuidWarningsResponse {
  const GetV1PosBanquetsUuidWarningsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosBanquetsUuidWarningsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1PosBanquetsUuidWarningsResponseFromJson(json);

  final bool success;
  final String message;
  final Data244 data;

  Map<String, Object?> toJson() =>
      _$GetV1PosBanquetsUuidWarningsResponseToJson(this);
}
