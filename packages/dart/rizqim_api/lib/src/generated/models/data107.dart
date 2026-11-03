// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'limits3.dart';
import 'stations3.dart';
import 'products6.dart';

part 'data107.g.dart';

@JsonSerializable()
class Data107 {
  const Data107({
    required this.branchId,
    required this.enabled,
    required this.expoScope,
    required this.limits,
    required this.stations,
    required this.products,
  });

  factory Data107.fromJson(Map<String, Object?> json) =>
      _$Data107FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final bool enabled;
  @JsonKey(name: 'expo_scope')
  final String expoScope;
  final Limits3 limits;
  final List<Stations3> stations;
  final List<Products6> products;

  Map<String, Object?> toJson() => _$Data107ToJson(this);
}
