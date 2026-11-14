// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'rates4.dart';

part 'halls.g.dart';

@JsonSerializable()
class Halls {
  const Halls({
    required this.hallId,
    required this.hallName,
    required this.branchId,
    required this.isActive,
    required this.rates,
  });

  factory Halls.fromJson(Map<String, Object?> json) => _$HallsFromJson(json);

  @JsonKey(name: 'hall_id')
  final int hallId;
  @JsonKey(name: 'hall_name')
  final String hallName;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'is_active')
  final bool isActive;
  final Rates4 rates;

  Map<String, Object?> toJson() => _$HallsToJson(this);
}
