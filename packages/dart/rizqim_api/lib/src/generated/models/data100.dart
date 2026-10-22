// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data100.g.dart';

@JsonSerializable()
class Data100 {
  const Data100({
    required this.id,
    required this.branchId,
    required this.diningTableId,
    required this.diningTableName,
    required this.hallId,
    required this.tableKind,
    required this.dayType,
    required this.price,
    required this.validFrom,
    required this.validTo,
  });

  factory Data100.fromJson(Map<String, Object?> json) =>
      _$Data100FromJson(json);

  final int id;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'dining_table_id')
  final int? diningTableId;
  @JsonKey(name: 'dining_table_name')
  final String? diningTableName;
  @JsonKey(name: 'hall_id')
  final int? hallId;
  @JsonKey(name: 'table_kind')
  final String? tableKind;
  @JsonKey(name: 'day_type')
  final String dayType;
  final String price;
  @JsonKey(name: 'valid_from')
  final String? validFrom;
  @JsonKey(name: 'valid_to')
  final String? validTo;

  Map<String, Object?> toJson() => _$Data100ToJson(this);
}
