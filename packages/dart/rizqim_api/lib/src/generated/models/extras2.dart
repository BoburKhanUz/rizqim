// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'extras2.g.dart';

@JsonSerializable()
class Extras2 {
  const Extras2({
    this.extraId,
    this.name,
    this.unitPrice,
    this.perPerson,
    this.quantity,
    this.note,
  });

  factory Extras2.fromJson(Map<String, Object?> json) =>
      _$Extras2FromJson(json);

  @JsonKey(name: 'extra_id')
  final int? extraId;
  final String? name;
  @JsonKey(name: 'unit_price')
  final num? unitPrice;
  @JsonKey(name: 'per_person')
  final bool? perPerson;
  final num? quantity;
  final String? note;

  Map<String, Object?> toJson() => _$Extras2ToJson(this);
}
