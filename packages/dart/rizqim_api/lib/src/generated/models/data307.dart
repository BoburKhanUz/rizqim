// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order11.dart';
import 'bills3.dart';
import 'refunds3.dart';

part 'data307.g.dart';

@JsonSerializable()
class Data307 {
  const Data307({
    required this.order,
    required this.bills,
    required this.refunds,
  });

  factory Data307.fromJson(Map<String, Object?> json) =>
      _$Data307FromJson(json);

  final Order11 order;
  final List<Bills3> bills;
  final List<Refunds3> refunds;

  Map<String, Object?> toJson() => _$Data307ToJson(this);
}
