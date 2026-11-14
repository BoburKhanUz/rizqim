// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order11.dart';
import 'bills3.dart';
import 'refunds3.dart';

part 'data301.g.dart';

@JsonSerializable()
class Data301 {
  const Data301({
    required this.order,
    required this.bills,
    required this.refunds,
  });

  factory Data301.fromJson(Map<String, Object?> json) =>
      _$Data301FromJson(json);

  final Order11 order;
  final List<Bills3> bills;
  final List<Refunds3> refunds;

  Map<String, Object?> toJson() => _$Data301ToJson(this);
}
