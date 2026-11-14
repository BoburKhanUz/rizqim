// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_banquets_uuid_start_request_body.g.dart';

@JsonSerializable()
class V1PosBanquetsUuidStartRequestBody {
  const V1PosBanquetsUuidStartRequestBody({this.version, this.waiterId});

  factory V1PosBanquetsUuidStartRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosBanquetsUuidStartRequestBodyFromJson(json);

  final int? version;
  @JsonKey(name: 'waiter_id')
  final int? waiterId;

  Map<String, Object?> toJson() =>
      _$V1PosBanquetsUuidStartRequestBodyToJson(this);
}
