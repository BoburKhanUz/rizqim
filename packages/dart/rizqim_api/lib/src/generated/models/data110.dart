// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data110.g.dart';

@JsonSerializable()
class Data110 {
  const Data110({
    required this.entryId,
    required this.branchId,
    required this.ingredientId,
    required this.reason,
    required this.until,
    required this.closedProductIds,
  });

  factory Data110.fromJson(Map<String, Object?> json) =>
      _$Data110FromJson(json);

  @JsonKey(name: 'entry_id')
  final int entryId;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'ingredient_id')
  final int ingredientId;
  final String reason;
  final String? until;
  @JsonKey(name: 'closed_product_ids')
  final Map<String, int> closedProductIds;

  Map<String, Object?> toJson() => _$Data110ToJson(this);
}
