// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'events4.dart';
import 'dishes4.dart';
import 'osh4.dart';

part 'data230.g.dart';

@JsonSerializable()
class Data230 {
  const Data230({
    required this.branchId,
    required this.from,
    required this.to,
    required this.events,
    required this.dishes,
    required this.osh,
  });

  factory Data230.fromJson(Map<String, Object?> json) =>
      _$Data230FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String from;
  final String to;
  final List<Events4> events;
  final List<Dishes4> dishes;
  final Osh4 osh;

  Map<String, Object?> toJson() => _$Data230ToJson(this);
}
