// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bill3.dart';
import 'order7.dart';

part 'data197.g.dart';

@JsonSerializable()
class Data197 {
  const Data197({required this.bill, required this.order});

  factory Data197.fromJson(Map<String, Object?> json) =>
      _$Data197FromJson(json);

  final Bill3 bill;
  final Order7 order;

  Map<String, Object?> toJson() => _$Data197ToJson(this);
}
