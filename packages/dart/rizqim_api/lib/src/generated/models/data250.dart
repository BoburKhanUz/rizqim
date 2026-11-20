// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bill3.dart';
import 'order6.dart';

part 'data250.g.dart';

@JsonSerializable()
class Data250 {
  const Data250({required this.bill, required this.order});

  factory Data250.fromJson(Map<String, Object?> json) =>
      _$Data250FromJson(json);

  final Bill3 bill;
  final Order6 order;

  Map<String, Object?> toJson() => _$Data250ToJson(this);
}
