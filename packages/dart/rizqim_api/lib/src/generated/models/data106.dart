// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'limits2.dart';
import 'stations2.dart';
import 'products5.dart';

part 'data106.g.dart';

@JsonSerializable()
class Data106 {
  const Data106({
    required this.branchId,
    required this.enabled,
    required this.limits,
    required this.stations,
    required this.products,
  });

  factory Data106.fromJson(Map<String, Object?> json) =>
      _$Data106FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final bool enabled;
  final Limits2 limits;
  final List<Stations2> stations;
  final List<Products5> products;

  Map<String, Object?> toJson() => _$Data106ToJson(this);
}
