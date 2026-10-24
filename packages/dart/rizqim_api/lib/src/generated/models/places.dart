// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'places.g.dart';

@JsonSerializable()
class Places {
  const Places({
    required this.tableId,
    required this.hallId,
    required this.roomFee,
    required this.minSpend,
    required this.minSpendMode,
  });

  factory Places.fromJson(Map<String, Object?> json) => _$PlacesFromJson(json);

  @JsonKey(name: 'table_id')
  final int tableId;
  @JsonKey(name: 'hall_id')
  final int hallId;
  @JsonKey(name: 'room_fee')
  final String roomFee;
  @JsonKey(name: 'min_spend')
  final String minSpend;
  @JsonKey(name: 'min_spend_mode')
  final String minSpendMode;

  Map<String, Object?> toJson() => _$PlacesToJson(this);
}
