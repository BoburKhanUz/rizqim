// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bill3.dart';
import 'order7.dart';

part 'data190.g.dart';

@JsonSerializable()
class Data190 {
  const Data190({required this.bill, required this.order});

  factory Data190.fromJson(Map<String, Object?> json) =>
      _$Data190FromJson(json);

  final Bill3 bill;
  final Order7 order;

  Map<String, Object?> toJson() => _$Data190ToJson(this);
}
