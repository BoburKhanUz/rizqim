// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_banquets_uuid_freeze_request_body.g.dart';

@JsonSerializable()
class V1PosBanquetsUuidFreezeRequestBody {
  const V1PosBanquetsUuidFreezeRequestBody({this.version});

  factory V1PosBanquetsUuidFreezeRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosBanquetsUuidFreezeRequestBodyFromJson(json);

  final int? version;

  Map<String, Object?> toJson() =>
      _$V1PosBanquetsUuidFreezeRequestBodyToJson(this);
}
