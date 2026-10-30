// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'limits.dart';
import 'stations.dart';
import 'products4.dart';

part 'data105.g.dart';

@JsonSerializable()
class Data105 {
  const Data105({
    required this.branchId,
    required this.enabled,
    required this.limits,
    required this.stations,
    required this.products,
  });

  factory Data105.fromJson(Map<String, Object?> json) =>
      _$Data105FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final bool enabled;
  final Limits limits;
  final List<Stations> stations;
  final List<Products4> products;

  Map<String, Object?> toJson() => _$Data105ToJson(this);
}
