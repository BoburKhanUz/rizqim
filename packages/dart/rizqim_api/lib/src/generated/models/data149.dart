// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order9.dart';
import 'bills2.dart';
import 'refunds2.dart';

part 'data149.g.dart';

@JsonSerializable()
class Data149 {
  const Data149({
    required this.order,
    required this.bills,
    required this.refunds,
  });

  factory Data149.fromJson(Map<String, Object?> json) =>
      _$Data149FromJson(json);

  final Order9 order;
  final List<Bills2> bills;
  final List<Refunds2> refunds;

  Map<String, Object?> toJson() => _$Data149ToJson(this);
}
