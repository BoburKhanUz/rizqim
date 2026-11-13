// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bill2.dart';
import 'order6.dart';

part 'data196.g.dart';

@JsonSerializable()
class Data196 {
  const Data196({required this.bill, required this.order});

  factory Data196.fromJson(Map<String, Object?> json) =>
      _$Data196FromJson(json);

  final Bill2 bill;
  final Order6 order;

  Map<String, Object?> toJson() => _$Data196ToJson(this);
}
