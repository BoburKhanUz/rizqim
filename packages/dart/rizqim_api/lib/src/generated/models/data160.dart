// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'items9.dart';

part 'data160.g.dart';

@JsonSerializable()
class Data160 {
  const Data160({required this.items, required this.total});

  factory Data160.fromJson(Map<String, Object?> json) =>
      _$Data160FromJson(json);

  final List<Items9> items;
  final String total;

  Map<String, Object?> toJson() => _$Data160ToJson(this);
}
