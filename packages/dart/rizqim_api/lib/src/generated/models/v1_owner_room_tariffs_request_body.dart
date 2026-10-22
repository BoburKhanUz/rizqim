// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'table_kind.dart';
import 'day_type.dart';

part 'v1_owner_room_tariffs_request_body.g.dart';

@JsonSerializable()
class V1OwnerRoomTariffsRequestBody {
  const V1OwnerRoomTariffsRequestBody({
    required this.branchId,
    required this.dayType,
    required this.price,
    this.diningTableId,
    this.hallId,
    this.tableKind,
    this.validFrom,
    this.validTo,
  });

  factory V1OwnerRoomTariffsRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerRoomTariffsRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'dining_table_id')
  final int? diningTableId;
  @JsonKey(name: 'hall_id')
  final int? hallId;
  @JsonKey(name: 'table_kind')
  final TableKind? tableKind;
  @JsonKey(name: 'day_type')
  final DayType dayType;
  final num price;
  @JsonKey(name: 'valid_from')
  final DateTime? validFrom;
  @JsonKey(name: 'valid_to')
  final DateTime? validTo;

  Map<String, Object?> toJson() => _$V1OwnerRoomTariffsRequestBodyToJson(this);
}
