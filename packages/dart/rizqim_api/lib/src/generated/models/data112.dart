// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'limits2.dart';
import 'stations2.dart';
import 'products5.dart';

part 'data112.g.dart';

@JsonSerializable()
class Data112 {
  const Data112({
    required this.branchId,
    required this.enabled,
    required this.expoScope,
    required this.limits,
    required this.stations,
    required this.products,
  });

  factory Data112.fromJson(Map<String, Object?> json) =>
      _$Data112FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final bool enabled;
  @JsonKey(name: 'expo_scope')
  final String expoScope;
  final Limits2 limits;
  final List<Stations2> stations;
  final List<Products5> products;

  Map<String, Object?> toJson() => _$Data112ToJson(this);
}
