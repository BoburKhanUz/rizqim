// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order8.dart';
import 'bills.dart';
import 'refunds.dart';

part 'data155.g.dart';

@JsonSerializable()
class Data155 {
  const Data155({
    required this.order,
    required this.bills,
    required this.refunds,
  });

  factory Data155.fromJson(Map<String, Object?> json) =>
      _$Data155FromJson(json);

  final Order8 order;
  final List<Bills> bills;
  final List<Refunds> refunds;

  Map<String, Object?> toJson() => _$Data155ToJson(this);
}
