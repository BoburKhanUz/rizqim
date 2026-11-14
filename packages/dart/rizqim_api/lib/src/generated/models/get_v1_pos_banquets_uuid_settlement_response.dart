// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data238.dart';

part 'get_v1_pos_banquets_uuid_settlement_response.g.dart';

@JsonSerializable()
class GetV1PosBanquetsUuidSettlementResponse {
  const GetV1PosBanquetsUuidSettlementResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosBanquetsUuidSettlementResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1PosBanquetsUuidSettlementResponseFromJson(json);

  final bool success;
  final String message;
  final Data238 data;

  Map<String, Object?> toJson() =>
      _$GetV1PosBanquetsUuidSettlementResponseToJson(this);
}
