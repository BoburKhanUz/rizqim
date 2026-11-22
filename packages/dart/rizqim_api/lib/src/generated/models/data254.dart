// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bill4.dart';
import 'order7.dart';

part 'data254.g.dart';

@JsonSerializable()
class Data254 {
  const Data254({required this.bill, required this.order});

  factory Data254.fromJson(Map<String, Object?> json) =>
      _$Data254FromJson(json);

  final Bill4 bill;
  final Order7 order;

  Map<String, Object?> toJson() => _$Data254ToJson(this);
}
