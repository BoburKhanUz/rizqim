// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bill.dart';
import 'order4.dart';

part 'data167.g.dart';

@JsonSerializable()
class Data167 {
  const Data167({required this.bill, required this.order});

  factory Data167.fromJson(Map<String, Object?> json) =>
      _$Data167FromJson(json);

  final Bill bill;
  final Order4 order;

  Map<String, Object?> toJson() => _$Data167ToJson(this);
}
