// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'table_kind.dart';
import 'day_type.dart';

part 'v1_owner_room_tariffs_id_request_body.g.dart';

@JsonSerializable()
class V1OwnerRoomTariffsIdRequestBody {
  const V1OwnerRoomTariffsIdRequestBody({
    this.branchId,
    this.diningTableId,
    this.hallId,
    this.tableKind,
    this.dayType,
    this.price,
    this.validFrom,
    this.validTo,
  });

  factory V1OwnerRoomTariffsIdRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerRoomTariffsIdRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final String? branchId;
  @JsonKey(name: 'dining_table_id')
  final int? diningTableId;
  @JsonKey(name: 'hall_id')
  final int? hallId;
  @JsonKey(name: 'table_kind')
  final TableKind? tableKind;
  @JsonKey(name: 'day_type')
  final DayType? dayType;
  final num? price;
  @JsonKey(name: 'valid_from')
  final DateTime? validFrom;
  @JsonKey(name: 'valid_to')
  final DateTime? validTo;

  Map<String, Object?> toJson() =>
      _$V1OwnerRoomTariffsIdRequestBodyToJson(this);
}
