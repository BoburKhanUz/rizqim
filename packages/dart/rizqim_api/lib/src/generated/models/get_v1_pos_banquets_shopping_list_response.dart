// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data223.dart';

part 'get_v1_pos_banquets_shopping_list_response.g.dart';

@JsonSerializable()
class GetV1PosBanquetsShoppingListResponse {
  const GetV1PosBanquetsShoppingListResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosBanquetsShoppingListResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1PosBanquetsShoppingListResponseFromJson(json);

  final bool success;
  final String message;
  final Data223 data;

  Map<String, Object?> toJson() =>
      _$GetV1PosBanquetsShoppingListResponseToJson(this);
}
