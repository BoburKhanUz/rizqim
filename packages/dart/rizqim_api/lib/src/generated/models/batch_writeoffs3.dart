// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'items44.dart';

part 'batch_writeoffs3.g.dart';

@JsonSerializable()
class BatchWriteoffs3 {
  const BatchWriteoffs3({required this.count, required this.items});

  factory BatchWriteoffs3.fromJson(Map<String, Object?> json) =>
      _$BatchWriteoffs3FromJson(json);

  final int count;
  final List<Items44> items;

  Map<String, Object?> toJson() => _$BatchWriteoffs3ToJson(this);
}
