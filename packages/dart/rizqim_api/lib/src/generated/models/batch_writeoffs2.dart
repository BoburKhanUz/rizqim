// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'items48.dart';

part 'batch_writeoffs2.g.dart';

@JsonSerializable()
class BatchWriteoffs2 {
  const BatchWriteoffs2({required this.count, required this.items});

  factory BatchWriteoffs2.fromJson(Map<String, Object?> json) =>
      _$BatchWriteoffs2FromJson(json);

  final int count;
  final List<Items48> items;

  Map<String, Object?> toJson() => _$BatchWriteoffs2ToJson(this);
}
