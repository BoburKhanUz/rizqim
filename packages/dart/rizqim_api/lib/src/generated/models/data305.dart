// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order12.dart';
import 'bills5.dart';
import 'refunds4.dart';

part 'data305.g.dart';

@JsonSerializable()
class Data305 {
  const Data305({
    required this.order,
    required this.bills,
    required this.refunds,
  });

  factory Data305.fromJson(Map<String, Object?> json) =>
      _$Data305FromJson(json);

  final Order12 order;
  final List<Bills5> bills;
  final List<Refunds4> refunds;

  Map<String, Object?> toJson() => _$Data305ToJson(this);
}
