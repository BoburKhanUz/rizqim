// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'events3.dart';
import 'dishes4.dart';
import 'osh4.dart';

part 'data229.g.dart';

@JsonSerializable()
class Data229 {
  const Data229({
    required this.branchId,
    required this.from,
    required this.to,
    required this.events,
    required this.dishes,
    required this.osh,
  });

  factory Data229.fromJson(Map<String, Object?> json) =>
      _$Data229FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String from;
  final String to;
  final List<Events3> events;
  final List<Dishes4> dishes;
  final Osh4 osh;

  Map<String, Object?> toJson() => _$Data229ToJson(this);
}
