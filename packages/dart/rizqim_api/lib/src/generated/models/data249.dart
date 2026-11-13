// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order11.dart';
import 'bills3.dart';
import 'refunds3.dart';

part 'data249.g.dart';

@JsonSerializable()
class Data249 {
  const Data249({
    required this.order,
    required this.bills,
    required this.refunds,
  });

  factory Data249.fromJson(Map<String, Object?> json) =>
      _$Data249FromJson(json);

  final Order11 order;
  final List<Bills3> bills;
  final List<Refunds3> refunds;

  Map<String, Object?> toJson() => _$Data249ToJson(this);
}
