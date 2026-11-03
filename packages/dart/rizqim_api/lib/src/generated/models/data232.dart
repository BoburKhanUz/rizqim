// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order10.dart';
import 'bills4.dart';
import 'refunds3.dart';

part 'data232.g.dart';

@JsonSerializable()
class Data232 {
  const Data232({
    required this.order,
    required this.bills,
    required this.refunds,
  });

  factory Data232.fromJson(Map<String, Object?> json) =>
      _$Data232FromJson(json);

  final Order10 order;
  final List<Bills4> bills;
  final List<Refunds3> refunds;

  Map<String, Object?> toJson() => _$Data232ToJson(this);
}
