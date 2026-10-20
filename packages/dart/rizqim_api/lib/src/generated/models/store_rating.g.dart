// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'store_rating.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

StoreRating _$StoreRatingFromJson(Map<String, dynamic> json) => StoreRating(
  id: (json['id'] as num).toInt(),
  userId: (json['user_id'] as num).toInt(),
  storeId: (json['store_id'] as num).toInt(),
  orderId: (json['order_id'] as num?)?.toInt(),
  rating: (json['rating'] as num).toInt(),
  comment: json['comment'] as String?,
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  user: json['user'] == null
      ? null
      : User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  order: json['order'] == null
      ? null
      : Order.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$StoreRatingToJson(StoreRating instance) =>
    <String, dynamic>{
      'id': instance.id,
      'user_id': instance.userId,
      'store_id': instance.storeId,
      'order_id': instance.orderId,
      'rating': instance.rating,
      'comment': instance.comment,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
      'user': instance.user,
      'store': instance.store,
      'order': instance.order,
    };
