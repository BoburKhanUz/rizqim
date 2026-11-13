// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order12.dart';
import 'bills5.dart';
import 'refunds4.dart';

part 'data254.g.dart';

@JsonSerializable()
class Data254 {
  const Data254({
    required this.order,
    required this.bills,
    required this.refunds,
  });

  factory Data254.fromJson(Map<String, Object?> json) =>
      _$Data254FromJson(json);

  final Order12 order;
  final List<Bills5> bills;
  final List<Refunds4> refunds;

  Map<String, Object?> toJson() => _$Data254ToJson(this);
}
