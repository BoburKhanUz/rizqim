// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bill3.dart';
import 'order6.dart';

part 'data185.g.dart';

@JsonSerializable()
class Data185 {
  const Data185({required this.bill, required this.copy, required this.order});

  factory Data185.fromJson(Map<String, Object?> json) =>
      _$Data185FromJson(json);

  final Bill3 bill;
  final bool copy;
  final Order6 order;

  Map<String, Object?> toJson() => _$Data185ToJson(this);
}
