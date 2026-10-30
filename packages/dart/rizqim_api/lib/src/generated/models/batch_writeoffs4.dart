// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'items54.dart';

part 'batch_writeoffs4.g.dart';

@JsonSerializable()
class BatchWriteoffs4 {
  const BatchWriteoffs4({required this.count, required this.items});

  factory BatchWriteoffs4.fromJson(Map<String, Object?> json) =>
      _$BatchWriteoffs4FromJson(json);

  final int count;
  final List<Items54> items;

  Map<String, Object?> toJson() => _$BatchWriteoffs4ToJson(this);
}
