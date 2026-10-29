// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order10.dart';
import 'bills4.dart';
import 'refunds3.dart';

part 'data215.g.dart';

@JsonSerializable()
class Data215 {
  const Data215({
    required this.order,
    required this.bills,
    required this.refunds,
  });

  factory Data215.fromJson(Map<String, Object?> json) =>
      _$Data215FromJson(json);

  final Order10 order;
  final List<Bills4> bills;
  final List<Refunds3> refunds;

  Map<String, Object?> toJson() => _$Data215ToJson(this);
}
