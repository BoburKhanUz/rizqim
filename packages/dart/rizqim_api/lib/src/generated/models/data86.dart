// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'status4.dart';

part 'data86.g.dart';

@JsonSerializable()
class Data86 {
  const Data86({
    required this.id,
    required this.amount,
    required this.card,
    required this.status,
    required this.notes,
    required this.resolutionNotes,
    required this.processedAt,
    required this.createdAt,
  });

  factory Data86.fromJson(Map<String, Object?> json) => _$Data86FromJson(json);

  final int id;
  final String amount;
  final String card;
  final Status4 status;
  final String? notes;
  @JsonKey(name: 'resolution_notes')
  final String? resolutionNotes;
  @JsonKey(name: 'processed_at')
  final String? processedAt;
  @JsonKey(name: 'created_at')
  final String createdAt;

  Map<String, Object?> toJson() => _$Data86ToJson(this);
}
