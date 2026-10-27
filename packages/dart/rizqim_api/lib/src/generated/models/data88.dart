// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'status4.dart';

part 'data88.g.dart';

@JsonSerializable()
class Data88 {
  const Data88({
    required this.id,
    required this.amount,
    required this.card,
    required this.status,
    required this.notes,
    required this.resolutionNotes,
    required this.processedAt,
    required this.createdAt,
    required this.storeId,
    required this.cardNumber,
    required this.processedBy,
    required this.updatedAt,
  });

  factory Data88.fromJson(Map<String, Object?> json) => _$Data88FromJson(json);

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
  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'card_number')
  final String cardNumber;
  @JsonKey(name: 'processed_by')
  final int? processedBy;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;

  Map<String, Object?> toJson() => _$Data88ToJson(this);
}
